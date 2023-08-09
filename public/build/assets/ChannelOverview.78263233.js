import{_ as k}from"./Button.47a00e17.js";import{_ as M}from"./Modal.c37b4e9f.js";import{_ as j}from"./MultiSelect.03a43400.js";import{g as y,K as C,h as q,c as v,a as u,w as m,q as E,o as s,b as e,f as d,l as r,t as c,d as O,u as a,k as N,F as P,n as f,O as S}from"./app.2df6fe58.js";import{r as F}from"./PencilSquareIcon.0fe1e8db.js";import{r as A}from"./CheckCircleIcon.0d90e487.js";import"./open-closed.5f597199.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.c208199c.js";const I={class:"flex flex-col md:flex-row space-x-2"},L={key:0,class:"text-gray-600"},T={key:1},U={key:2},Y={class:"flex flex-col"},z={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5"},J={class:"inline-block min-w-full py-2 align-middle md:px-4 lg:px-6"},K={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},G={class:"table-fixed min-w-full divide-y divide-gray-300"},H={class:"bg-gray-50"},Q=e("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," # ",-1),R={key:0,scope:"col",class:"w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},W=e("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Sold ",-1),X=e("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Bal ",-1),Z=e("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Cap ",-1),ee={scope:"col",class:"w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},te={key:0},se=e("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Error ",-1),oe={key:1,scope:"col",class:"w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},de={key:2,scope:"col",class:"w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},ne={class:"bg-white"},re={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},ce={key:0,class:"whitespace-nowrap text-sm font-semibold text-gray-900 text-center"},ae={class:"flex justify-center items-center"},le=["src"],ie={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-blue-600 sm:pl-6 text-center"},pe={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},ue={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},me={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},_e={class:"py-1 pl-1 pr-1 text-xs font-medium text-gray-900 sm:pl-1 text-center"},xe={key:0},ye={class:"font-bold"},ve={key:1,class:"py-4 text-sm font-semibold text-gray-900 text-center"},fe={key:0},ge={key:0},he={key:1,class:"break-normal text-xs"},be=e("br",null,null,-1),we={key:1,class:"font-normal text-xs text-gray-700"},ke={key:2,class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},Ce={class:"flex justify-center space-x-1"},Oe=e("span",null," Dispense ",-1),Se={class:"flex justify-between"},De={key:0,class:"flex space-x-1"},Ve=e("span",null," Edit Product ",-1),$e={key:1,class:"flex space-x-1"},Be=e("span",null," Save ",-1),Me={key:0,class:"flex flex-col text-blue-800 text-sm p-3"},je={key:0,class:""},qe={key:1},Ue={__name:"ChannelOverview",props:{productOptions:Object,vend:Object,showModal:Boolean},emits:["modalClose"],setup(n,{emit:g}){const i=n,_=y([]),h=C().props.auth.permissions,b=y([]);q(()=>{b.value=i.productOptions.data.map(o=>({id:o.id,full_name:o.full_name+(o.desc?" "+o.desc:"")})),_.value=i.vend.vendChannelsJson.map(o=>({...o,product:o.product?{...o.product,option_data:{id:o.product.id,full_name:o.product.code+" - "+o.product.name+(o.product.desc?" "+o.product.desc:"")}}:null}))});const x=C().props.auth.profile,l=y(!1);function D(o){S.post("/vends/"+i.vend.id+"/dispense-product",{channel_id:o.id},{preserveScroll:!0,onSuccess:()=>{g("modalClose")}})}function V(){l.value?S.post("/vends/"+i.vend.id+"/edit-products",{channels:_.value.map(o=>({id:o.id,product_id:o.product.id,edited_product_id:o.product.option_data.id}))},{preserveScroll:!0,onSuccess:()=>{l.value=!1,g("modalClose")}}):l.value=!0}function $(o){return moment(o).format("YYMMDD hh:mm A")}return(o,p)=>(s(),v(E,{to:"body"},[u(M,{open:n.showModal,onModalClose:p[1]||(p[1]=t=>o.$emit("modalClose"))},{header:m(()=>[e("div",I,[i.profile?(s(),d("span",L," Channel Overview ")):r("",!0),n.vend.code?(s(),d("span",T," ID# "+c(n.vend.code),1)):r("",!0),n.vend.customer_code?(s(),d("span",U," ("+c(n.vend.customer_code?n.vend.customer_code:null)+") "+c(n.vend.customer_name?n.vend.customer_name:null),1)):r("",!0)])]),default:m(()=>[e("div",Y,[e("div",z,[e("div",J,[e("div",K,[e("table",G,[e("thead",H,[e("tr",null,[Q,n.vend.product_mapping_name?(s(),d("th",R," Image ")):r("",!0),W,X,Z,e("th",ee,[O(" Price "),a(x)&&a(x).base_currency?(s(),d("span",te," ("+c(a(x).base_currency.currency_symbol)+") ",1)):r("",!0)]),se,n.vend.product_mapping_name?(s(),d("th",oe," Product ")):r("",!0),a(h).includes("admin-access vends")&&n.vend.is_mqtt?(s(),d("th",de," Action ")):r("",!0)])]),e("tbody",ne,[(s(!0),d(P,null,N(_.value,(t,B)=>(s(),d("tr",{key:t.id,class:f(B%2===0?void 0:"bg-gray-50")},[e("td",re,c(t.code),1),n.vend.product_mapping_name?(s(),d("td",ce,[e("div",ae,[t.product&&t.product.thumbnail?(s(),d("img",{key:0,class:"h-16 w-16 rounded-full",src:t.product.thumbnail.full_url,alt:""},null,8,le)):r("",!0)])])):r("",!0),e("td",ie,c(t.capacity-t.qty),1),e("td",pe,c(t.qty),1),e("td",ue,c(t.capacity),1),e("td",me,c((t.amount/100).toLocaleString(void 0,{minimumFractionDigits:2})),1),e("td",_e,[t.vend_channel_error_logs&&t.vend_channel_error_logs[0]&&t.vend_channel_error_logs[0].is_error_cleared==0?(s(),d("span",xe,[e("div",{class:f([t.vend_channel_error_logs[0].vend_channel_error.code==4||t.vend_channel_error_logs[0].vend_channel_error.code==5||t.vend_channel_error_logs[0].vend_channel_error.code==7?" text-blue-800":" text-red-800"])},[e("span",ye," ("+c(t.vend_channel_error_logs[0].vend_channel_error.code)+") ",1),e("div",null,c($(t.vend_channel_error_logs[0].created_at)),1)],2)])):r("",!0)]),n.vend.product_mapping_name?(s(),d("td",ve,[l.value?(s(),d("span",we,[u(j,{modelValue:t.product.option_data,"onUpdate:modelValue":w=>t.product.option_data=w,options:b.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","onUpdate:modelValue","options"])])):(s(),d("span",fe,[t.product&&t.product.code?(s(),d("span",ge,c(t.product.code),1)):r("",!0),t.product&&t.product.name?(s(),d("span",he,[be,O(" "+c(t.product.name),1)])):r("",!0)]))])):r("",!0),a(h).includes("admin-access vends")?(s(),d("td",ke,[e("div",Ce,[n.vend.is_mqtt?(s(),v(k,{key:0,type:"button",class:"bg-yellow-300 hover:bg-yellow-400 px-1 py-1 text-xs text-gray-800 flex space-x-1",onClick:w=>D(t)},{default:m(()=>[Oe]),_:2},1032,["onClick"])):r("",!0)])])):r("",!0)],2))),128))])])])])])]),e("div",Se,[e("span",null,[n.vend.product_mapping_name?(s(),v(k,{key:0,type:"button",class:f(["px-2 py-1 mt-2 ml-1 text-xs flex space-x-1",[l.value?"bg-green-300 hover:bg-green-400 text-green-800":"bg-gray-300 hover:bg-gray-400 text-gray-800"]]),onClick:p[0]||(p[0]=t=>V())},{default:m(()=>[l.value?(s(),d("span",$e,[u(a(A),{class:"w-4 h-4"}),Be])):(s(),d("span",De,[u(a(F),{class:"w-4 h-4"}),Ve]))]),_:1},8,["class"])):r("",!0)]),n.vend.product_mapping_name?(s(),d("span",Me,[n.vend.product_mapping_name?(s(),d("span",je,c(n.vend.product_mapping_name),1)):r("",!0),n.vend.product_mapping_remarks?(s(),d("span",qe,c(n.vend.product_mapping_remarks),1)):r("",!0)])):r("",!0)])]),_:1},8,["open"])]))}};export{Ue as default};
