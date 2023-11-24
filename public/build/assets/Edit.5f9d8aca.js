import{g as m,T as D,K as E,h as re,f as r,a as d,u as c,w as u,F as V,o as a,Z as le,b as e,d as _,t as p,l as i,e as y,i as ne,c as g,n as x,k as M,O as h}from"./app.b5f13399.js";import{_ as ie}from"./Authenticated.0e6b1026.js";import{_ as f}from"./Button.3320ffca.js";import{r as $,_ as de}from"./ChannelOverview.12cae514.js";import"./main.81bcaca0.js";import{_ as b}from"./FormInput.283c99a5.js";import{_ as U}from"./MultiSelect.fca74f6f.js";import{r as ce}from"./ArrowUturnLeftIcon.099d2ea7.js";import{r as pe}from"./CheckCircleIcon.0f1dd75d.js";import{r as L}from"./PlusCircleIcon.849328cf.js";import{r as B}from"./PauseCircleIcon.d51dc0a6.js";import{r as ue}from"./BackspaceIcon.969e4fd0.js";import"./open-closed.f1cdc962.js";import"./use-resolve-button-type.b11c005f.js";import"./RectangleStackIcon.79e81786.js";import"./Modal.c420f995.js";import"./disposables.a8d651ef.js";import"./PencilSquareIcon.ad09e765.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.bed5e2be.js";const me={class:"font-semibold text-xl text-gray-800 leading-tight"},_e={key:0},ve={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},xe={class:"mt-6 flex flex-col"},ge={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},fe={class:"shadow-sm ring-1 ring-black ring-opacity-5 p-5"},ye=["onSubmit"],he={class:"grid grid-cols-1 gap-3 sm:grid-cols-6 pb-2"},be={class:"sm:col-span-6"},we={class:"sm:col-span-6"},ke=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Operator ",-1),Se={class:"mt-1"},Ce=["value"],Ve={class:"sm:col-span-6"},Oe=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Delivery Platform ",-1),Pe={class:"mt-1"},je=["value"],Ae={class:"sm:col-span-6"},De=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Platform Category ",-1),Me={class:"mt-1"},$e=["value"],Ue={class:"sm:col-span-6"},Be=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Refer to Product Mapping ",-1),Re={class:"mt-1"},Ne=["value"],qe={class:"sm:col-span-3"},Fe={class:"sm:col-span-3"},Ke=e("div",{class:"sm:col-span-6"},[e("label",{for:"reserved",class:"italic text-blue-800"},' By setting "Reserved Percentage" and "Reserved Quantity", the sellable qty equivalent to whichever higher. If lower than reserved, channel becomes inactive, both default value are 0. ')],-1),Te={class:"sm:col-span-6"},Ee={class:"flex space-x-1 mt-5 justify-end"},Le=e("span",null," Back ",-1),Qe=e("span",null," Save ",-1),ze=e("div",{class:"sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3"},[e("div",{class:"relative"},[e("div",{class:"absolute inset-0 flex items-center","aria-hidden":"true"},[e("div",{class:"w-full border-t border-gray-300"})]),e("div",{class:"relative flex justify-center"},[e("span",{class:"px-3 bg-white text-lg font-medium text-gray-900 rounded"},"Delivery Platform Product(s) ")])])],-1),Ze={key:0},Ge={key:1},He=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Product ",-1),Je={key:0,class:"text-sm text-red-600"},We={key:2},Xe={key:3},Ye=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Platform SubCategory ",-1),Ie={key:4,class:"sm:col-span-1"},et=e("span",null," Add ",-1),tt={key:5,class:"sm:col-span-6 flex flex-col mt-3"},st={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5"},ot={class:"inline-block min-w-full py-2 align-middle md:px-4 lg:px-6"},at={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},rt={class:"table-fixed min-w-full divide-y divide-gray-300"},lt=e("thead",{class:"bg-gray-50"},[e("tr",null,[e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," # "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Channel ID "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Thumbnail "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Product "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Status "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Price "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Platform SubCategory "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Action ")])],-1),nt={class:"bg-white"},it={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},dt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},ct={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},pt={class:"flex justify-center"},ut=["src"],mt={class:"whitespace-normal py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left flex flex-col"},_t={key:0},vt={key:1,class:"break-words"},xt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},gt={key:0,class:"inline-flex items-center rounded-md bg-green-300 px-1.5 py-0.5 text-xs font-medium text-green-800 ring-1 ring-inset ring-indigo-700/10"},ft={key:1,class:"inline-flex items-center rounded-md bg-red-200 px-1.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-indigo-700/10"},yt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},ht={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},bt={class:"whitespace-nowrap py-4 text-sm text-center flex flex-col space-y-1 px-2"},wt={key:2,class:"text-xs"},kt={key:3,class:"text-xs"},St=e("span",{class:"text-xs"}," Unbind SKU ",-1),Ct={key:0},Vt=e("td",{colspan:"7",class:"whitespace-nowrap py-4 text-sm font-medium text-gray-600 text-center"}," No Records Found ",-1),Ot=[Vt],Pt={key:6,class:"sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3"},jt=e("div",{class:"relative"},[e("div",{class:"absolute inset-0 flex items-center","aria-hidden":"true"},[e("div",{class:"w-full border-t border-gray-300"})]),e("div",{class:"relative flex justify-center"},[e("span",{class:"px-3 bg-white text-lg font-medium text-gray-900 rounded"}," Vending Machine Binding ")])],-1),At=[jt],Dt={class:"sm:col-span-6"},Mt={class:"flex space-x-1 mt-5 justify-start"},$t={key:2},Ut={key:3},Bt={key:7,class:"sm:col-span-3"},Rt=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Vending Machine ",-1),Nt={key:0,class:"text-sm text-red-600"},qt={key:8,class:"sm:col-span-2"},Ft={key:9,class:"sm:col-span-1"},Kt=e("span",null," Add ",-1),Tt={key:10,class:"sm:col-span-6 flex flex-col mt-3"},Et={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5"},Lt={class:"inline-block min-w-full py-2 align-middle md:px-4 lg:px-6"},Qt={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},zt={class:"min-w-full divide-y divide-gray-300"},Zt=e("thead",{class:"bg-gray-50"},[e("tr",null,[e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," # "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"},[_(" Vend ID "),e("br"),_(" (Platform Ref ID) ")]),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Vend Name "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Channel Status "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," VM Status "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Action ")])],-1),Gt={class:"bg-white"},Ht={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},Jt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},Wt=e("br",null,null,-1),Xt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left"},Yt={key:0},It=e("br",null,null,-1),es={key:1},ts={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},ss=["onClick"],os={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},as={key:0,class:"inline-flex items-center rounded-md bg-green-300 px-1.5 py-0.5 text-xs font-medium text-green-800 ring-1 ring-inset ring-indigo-700/10"},rs={key:1,class:"inline-flex items-center rounded-md bg-red-200 px-1.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-indigo-700/10"},ls={class:"whitespace-nowrap py-2 text-xs text-center p-3"},ns={class:"flex flex-col space-y-1"},is={key:2,class:"text-xs"},ds={key:3,class:"text-xs"},cs={key:0},ps=e("td",{colspan:"6",class:"whitespace-nowrap py-4 text-sm font-medium text-gray-600 text-center"}," No Records Found ",-1),us=[ps],Ms={__name:"Edit",props:{categoryApiOptions:[Array,Object],deliveryProductMapping:Object,operatorOptions:Object,productMappingItems:Object,productMappingOptions:[Array,Object],productOptions:Object,type:String,unbindedVendOptions:[Array,Object]},setup(R){const n=R,Q=m([]),O=m([]),z=m([]),Z=m([]),t=m(D(T()));m(!1);const k=E().props.auth.operatorCountry,P=m([]),j=m([]),G=m([]),N=m([]),S=m(!1),q=m([]),A=m(""),H=E().props.auth.permissions,F=m(),J=m([]),K=m([]);re(()=>{n.type=="create"?A.value="Create New":A.value="Edit",Q.value=n.categoryApiOptions[0].categories.map(o=>({id:o.id,name:o.name,subCategories:o.subCategories})),O.value=[...n.operatorOptions.data.find(o=>o.id===n.deliveryProductMapping.data.operator_id).deliveryPlatformOperators.map(o=>({id:o.id,name:o.deliveryPlatform.name+" ("+o.type+")"}))],Z.value=n.deliveryProductMapping?n.deliveryProductMapping.data.deliveryProductMappingItems:[],P.value=[...n.operatorOptions.data.map(o=>({id:o.id,full_name:o.full_name}))],j.value=[...n.productMappingOptions[0].data.map(o=>({id:o.id,name:o.name}))],N.value=n.productOptions.data,t.value=n.deliveryProductMapping?D({...n.deliveryProductMapping.data,operator_id:P.value.find(o=>o.id===n.deliveryProductMapping.data.operator_id),operator_field:P.value.find(o=>o.id===n.deliveryProductMapping.data.operator_id).full_name,product_mapping_id:j.value.find(o=>o.id===n.deliveryProductMapping.data.product_mapping_id),product_mapping_field:j.value.find(o=>o.id===n.deliveryProductMapping.data.product_mapping_id).name,delivery_platform_operator_id:O.value.find(o=>o.id===n.deliveryProductMapping.data.delivery_platform_operator_id),delivery_platform_operator_field:O.value.find(o=>o.id===n.deliveryProductMapping.data.delivery_platform_operator_id).name}):D(T()),q.value=n.deliveryProductMapping?n.deliveryProductMapping.data.category_json.subCategories:[],K.value=[...n.unbindedVendOptions.data.map(o=>({id:o.id,full_name:o.full_name}))],J.value=n.deliveryProductMapping?n.deliveryProductMapping.data.vends:[]});function T(){return{id:"",category_json:"",delivery_platform_operator_id:"",name:"",operator_id:"",platform_ref_id:"",product_mapping_id:"",reserved_percent:0,reserved_qty:0,sub_category_json:""}}function W(){G.value.map(function(o){return o.channel_code}).indexOf(t.value.channel_code)<0&&h.post("/delivery-product-mapping-items/delivery-product-mapping/"+t.value.id+"/store",{...t.value,product_id:t.value.product_id?t.value.product_id.id:null},{preserveState:!1,preserveScroll:!0,replace:!0})}function X(o){h.post("/delivery-product-mappings/"+t.value.id+"/bind-vend",{vend_id:o,platform_ref_id:t.value.platform_ref_id},{preserveState:!1,preserveScroll:!0,replace:!0})}function Y(o){F.value=o,S.value=!0}function I(){S.value=!1}function ee(){t.value.clearErrors(),t.value.transform(o=>({name:o.name,reserved_percent:o.reserved_percent,reserved_qty:o.reserved_qty})).post("/delivery-product-mappings/"+t.value.id+"/update",{preserveState:!0,replace:!0})}function te(o){let l=t.value.is_active?"Are you sure to pause all vending machines?":"Are you sure to resume all vending machines?";!confirm(l)||h.post("/delivery-product-mappings/"+o+"/toggle-pause-all-vends",{},{preserveState:!1,preserveScroll:!0,replace:!0})}function se(o){let l=o.is_active?"Are you sure to pause this SKU?":"Are you sure to resume this SKU?";!confirm(l)||h.post("/delivery-product-mapping-items/"+o.id+"/toggle-pause",{},{preserveState:!1,preserveScroll:!0,replace:!0})}function oe(o){let l=o.is_active?"Are you sure to pause this vending machine?":"Are you sure to resume this vending machine?";!confirm(l)||h.post("/delivery-product-mappings/vends/"+o.id+"/toggle-pause-vend",{},{preserveState:!1,preserveScroll:!0,replace:!0})}function ae(o){!confirm("Are you sure to delete this entry?")||h.delete("/delivery-product-mapping-items/"+o,{preserveState:!1,preserveScroll:!0,replace:!0})}return(o,l)=>(a(),r(V,null,[d(c(le),{title:"Delivery Product Mapping"}),d(ie,null,{header:u(()=>[e("h2",me,[_(p(A.value)+" Delivery Product Mapping ",1),R.type=="update"?(a(),r("span",_e,p(z.value.name),1)):i("",!0)])]),default:u(()=>[e("div",ve,[e("div",xe,[e("div",ge,[e("div",fe,[e("form",{onSubmit:y(ee,["prevent"]),id:"submit"},[e("div",he,[e("div",be,[d(b,{modelValue:t.value.name,"onUpdate:modelValue":l[0]||(l[0]=s=>t.value.name=s),error:t.value.errors.name},{default:u(()=>[_(" Name ")]),_:1},8,["modelValue","error"])]),e("div",we,[ke,e("div",Se,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed",value:t.value.operator_field,disabled:""},null,8,Ce)])]),e("div",Ve,[Oe,e("div",Pe,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed",value:t.value.delivery_platform_operator_field,disabled:""},null,8,je)])]),e("div",Ae,[De,e("div",Me,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed",value:t.value.category_json.name,disabled:""},null,8,$e)])]),e("div",Ue,[Be,e("div",Re,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed",value:t.value.product_mapping_field,disabled:""},null,8,Ne)])]),e("div",qe,[d(b,{modelValue:t.value.reserved_percent,"onUpdate:modelValue":l[1]||(l[1]=s=>t.value.reserved_percent=s),error:t.value.errors.reserved_percent},{default:u(()=>[_(" Reserved Percentage (%) ")]),_:1},8,["modelValue","error"])]),e("div",Fe,[d(b,{modelValue:t.value.reserved_qty,"onUpdate:modelValue":l[2]||(l[2]=s=>t.value.reserved_qty=s),error:t.value.errors.reserved_qty},{default:u(()=>[_(" Reserved Quantity ")]),_:1},8,["modelValue","error"])]),Ke,e("div",Te,[e("div",Ee,[d(c(ne),{href:"/delivery-product-mappings"},{default:u(()=>[d(f,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"},{default:u(()=>[d(c(ce),{class:"w-4 h-4"}),Le]),_:1})]),_:1}),c(H).includes("update vends")?(a(),g(f,{key:0,type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:u(()=>[d(c(pe),{class:"w-4 h-4"}),Qe]),_:1})):i("",!0)])]),ze,t.value.id?(a(),r("div",Ze,[d(b,{modelValue:t.value.channel_code,"onUpdate:modelValue":l[3]||(l[3]=s=>t.value.channel_code=s),error:t.value.errors.channel_code,placeholderStr:"Channel ID"},{default:u(()=>[_(" Channel ID ")]),_:1},8,["modelValue","error"])])):i("",!0),t.value.id?(a(),r("div",Ge,[He,d(U,{modelValue:t.value.product_id,"onUpdate:modelValue":l[4]||(l[4]=s=>t.value.product_id=s),options:N.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),t.value.errors.product_id?(a(),r("div",Je,p(t.value.errors.product_id),1)):i("",!0)])):i("",!0),t.value.id?(a(),r("div",We,[d(b,{modelValue:t.value.amount,"onUpdate:modelValue":l[5]||(l[5]=s=>t.value.amount=s),error:t.value.errors.amount,placeholderStr:"Platform Price"},{default:u(()=>[_(" Price ")]),_:1},8,["modelValue","error"])])):i("",!0),t.value.id?(a(),r("div",Xe,[Ye,d(U,{modelValue:t.value.sub_category_json,"onUpdate:modelValue":l[6]||(l[6]=s=>t.value.sub_category_json=s),options:q.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):i("",!0),t.value.product_mapping_id?(a(),r("div",Ie,[d(f,{type:"button",onClick:l[7]||(l[7]=y(s=>W(),["prevent"])),class:x(["bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6",[!t.value.channel_code||!t.value.product_id?"opacity-50 cursor-not-allowed":""]]),disabled:!t.value.channel_code||!t.value.product_id},{default:u(()=>[d(c(L),{class:"w-4 h-4"}),et]),_:1},8,["class","disabled"])])):i("",!0),t.value.product_mapping_id?(a(),r("div",tt,[e("div",st,[e("div",ot,[e("div",at,[e("table",rt,[lt,e("tbody",nt,[(a(!0),r(V,null,M(n.deliveryProductMapping.data.deliveryProductMappingItems,(s,w)=>(a(),r("tr",{key:s.id,class:x(w%2===0?void 0:"bg-gray-50")},[e("td",it,p(w+1),1),e("td",dt,p(s.channel_code),1),e("td",ct,[e("div",pt,[s.product&&s.product.thumbnail?(a(),r("img",{key:0,class:"h-24 w-24 md:h-20 md:w-20 rounded-full",src:s.product.thumbnail.full_url,alt:""},null,8,ut)):i("",!0)])]),e("td",mt,[s.product.code?(a(),r("span",_t,p(s.product.code),1)):i("",!0),s.product.name?(a(),r("span",vt,p(s.product.name),1)):i("",!0)]),e("td",xt,[s.is_active==1?(a(),r("span",gt," Active ")):i("",!0),s.is_active==0?(a(),r("span",ft," Paused ")):i("",!0)]),e("td",yt,p(s.amount.toLocaleString(void 0,{minimumFractionDigits:c(k).is_currency_exponent_hidden?0:c(k).currency_exponent,maximumFractionDigits:c(k).is_currency_exponent_hidden?0:c(k).currency_exponent})),1),e("td",ht,p(s.sub_category_json.name),1),e("td",bt,[d(f,{class:x(["flex space-x-1",[s.is_active?"bg-yellow-300 hover:bg-yellow-400 text-black":"bg-green-500 hover:bg-green-600 text-white"]]),onClick:y(v=>se(s),["prevent"])},{default:u(()=>[s.is_active?(a(),g(c(B),{key:0,class:"w-3 h-3"})):(a(),g(c($),{key:1,class:"w-3 h-3"})),s.is_active?(a(),r("span",wt," Pause SKU ")):(a(),r("span",kt," Resume SKU "))]),_:2},1032,["class","onClick"]),d(f,{class:"bg-red-400 hover:bg-red-500 text-white flex space-x-1",onClick:y(v=>ae(s.id),["prevent"])},{default:u(()=>[d(c(ue),{class:"w-3 h-3"}),St]),_:2},1032,["onClick"])])],2))),128)),!n.deliveryProductMapping.data.deliveryProductMappingItems||!n.deliveryProductMapping.data.deliveryProductMappingItems.length?(a(),r("tr",Ct,Ot)):i("",!0)])])])])])])):i("",!0),t.value.product_mapping_id?(a(),r("div",Pt,At)):i("",!0),e("div",Dt,[e("div",Mt,[d(f,{class:x(["flex space-x-1",[t.value.is_active?"bg-yellow-300 hover:bg-yellow-400 text-black-700":"bg-green-500 hover:bg-green-600 text-white"]]),onClick:l[8]||(l[8]=y(s=>te(t.value.id),["prevent"]))},{default:u(()=>[t.value.is_active?(a(),g(c(B),{key:0,class:"w-4 h-4"})):(a(),g(c($),{key:1,class:"w-4 h-4"})),t.value.is_active?(a(),r("span",$t," Pause All Vending Machines ")):(a(),r("span",Ut," Resume All Vending Machines "))]),_:1},8,["class"])])]),t.value.product_mapping_id?(a(),r("div",Bt,[Rt,d(U,{modelValue:t.value.vend_id,"onUpdate:modelValue":l[9]||(l[9]=s=>t.value.vend_id=s),options:K.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),t.value.errors.vend_id?(a(),r("div",Nt,p(t.value.errors.vend_id),1)):i("",!0)])):i("",!0),t.value.product_mapping_id?(a(),r("div",qt,[d(b,{modelValue:t.value.platform_ref_id,"onUpdate:modelValue":l[10]||(l[10]=s=>t.value.platform_ref_id=s),error:t.value.errors.platform_ref_id,placeholderStr:"Platform ID"},{default:u(()=>[_(" Platform ID (Store ID) ")]),_:1},8,["modelValue","error"])])):i("",!0),t.value.product_mapping_id?(a(),r("div",Ft,[d(f,{type:"button",onClick:l[11]||(l[11]=y(s=>X(t.value.vend_id.id),["prevent"])),class:x(["bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6",[t.value.vend_id?"":"opacity-50 cursor-not-allowed"]]),disabled:!t.value.vend_id},{default:u(()=>[d(c(L),{class:"w-4 h-4"}),Kt]),_:1},8,["class","disabled"])])):i("",!0),t.value.product_mapping_id?(a(),r("div",Tt,[e("div",Et,[e("div",Lt,[e("div",Qt,[e("table",zt,[Zt,e("tbody",Gt,[(a(!0),r(V,null,M(n.deliveryProductMapping.data.deliveryProductMappingVends,(s,w)=>(a(),r("tr",{key:s.id,class:x(w%2===0?void 0:"bg-gray-50")},[e("td",Ht,p(w+1),1),e("td",Jt,[_(p(s.vend.code)+" ",1),Wt,_(" "+p(s.platform_ref_id),1)]),e("td",Xt,[s.vend.latestVendBinding&&s.vend.latestVendBinding.customer?(a(),r("span",Yt,[_(p(s.vend.latestVendBinding.customer.code)+" ",1),It,_(" "+p(s.vend.latestVendBinding.customer.name),1)])):(a(),r("span",es,p(s.vend.name),1))]),e("td",ts,[s.deliveryProductMappingVendChannels?(a(),r("ul",{key:0,class:"sm:grid sm:grid-cols-[105px_minmax(110px,_1fr)_100px] hover:cursor-pointer",onClick:v=>Y(s)},[(a(!0),r(V,null,M(s.deliveryProductMappingVendChannels,(v,C)=>(a(),r("li",{class:x(["quick-look",[C>0&&String(v.vend_channel_code)[0]!==String(s.deliveryProductMappingVendChannels[C-1].vend_channel_code)[0]?"col-start-1":""]])},[e("span",{class:x([[C>0&&String(v.vend_channel_code)[0]!==String(s.deliveryProductMappingVendChannels[C-1].vend_channel_code)[0]?"border-t-4 pt-1":""],"flex space-x-1"])},[e("span",null," #"+p(v.vend_channel_code)+", ",1),e("span",null,p(v.delivery_product_mapping_item.product?v.delivery_product_mapping_item.product.code:""),1),e("span",{class:x(["inline-flex items-center rounded-md px-1.5 text-xs font-medium text-green-800 ring-1 ring-inset ring-indigo-700/10",[v.is_active==1?"bg-green-500":"bg-red-500"]])},null,2)],2)],2))),256))],8,ss)):i("",!0)]),e("td",os,[s.is_active==1?(a(),r("span",as," Operating ")):i("",!0),s.is_active==0?(a(),r("span",rs," Paused ")):i("",!0)]),e("td",ls,[e("div",ns,[d(f,{class:x(["flex space-x-1",[s.is_active?"bg-yellow-300 hover:bg-yellow-400 text-black":"bg-green-500 hover:bg-green-600 text-white"]]),onClick:y(v=>oe(s),["prevent"])},{default:u(()=>[s.is_active?(a(),g(c(B),{key:0,class:"w-3 h-3"})):(a(),g(c($),{key:1,class:"w-3 h-3"})),s.is_active?(a(),r("span",is," Pause VM ")):(a(),r("span",ds," Resume VM "))]),_:2},1032,["class","onClick"])])])],2))),128)),n.deliveryProductMapping.data.deliveryProductMappingVends.length?i("",!0):(a(),r("tr",cs,us))])])])])])])):i("",!0)])],40,ye)])])])]),S.value?(a(),g(de,{key:0,vend:F.value,showModal:S.value,onModalClose:I},null,8,["vend","showModal"])):i("",!0)]),_:1})],64))}};export{Ms as default};
