import{g as m,T as $,K as E,h as ne,f as r,a as i,u as c,w as p,F as V,o as a,Z as ie,b as e,d as g,t as u,l as d,e as f,i as de,c as y,n as v,k as D,O as h}from"./app.b481dd46.js";import{_ as ce}from"./Authenticated.8d2079bf.js";import{_ as x}from"./Button.7cb52733.js";import{r as U,_ as pe}from"./ChannelOverview.bd943cc7.js";import"./main.70a263b3.js";import{_ as w}from"./FormInput.fd5000e1.js";import{_ as B}from"./MultiSelect.50e292fc.js";import{r as ue}from"./ArrowUturnLeftIcon.7f4b62df.js";import{r as me}from"./CheckCircleIcon.0fb62446.js";import{r as L}from"./PlusCircleIcon.b2a3d391.js";import{r as N}from"./PauseCircleIcon.11a9a42f.js";import{r as Q}from"./BackspaceIcon.6f68a504.js";import"./open-closed.28eb0ea4.js";import"./use-resolve-button-type.f2d2bbd0.js";import"./RectangleStackIcon.31d0d94c.js";import"./Modal.c591cbe4.js";import"./disposables.04539461.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.d0a7830e.js";const _e={class:"font-semibold text-xl text-gray-800 leading-tight"},ve={key:0},xe={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},ge={class:"mt-6 flex flex-col"},fe={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ye={class:"shadow-sm ring-1 ring-black ring-opacity-5 p-5"},he=["onSubmit"],be={class:"grid grid-cols-1 gap-3 sm:grid-cols-6 pb-2"},we={class:"sm:col-span-6"},ke={class:"sm:col-span-6"},Se=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Operator ",-1),Ce={class:"mt-1"},Ve=["value"],Oe={class:"sm:col-span-6"},Pe=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Delivery Platform ",-1),je={class:"mt-1"},Ae=["value"],Me={class:"sm:col-span-6"},$e=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Platform Category ",-1),De={class:"mt-1"},Ue=["value"],Be={class:"sm:col-span-6"},Ne=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Refer to Product Mapping ",-1),Re={class:"mt-1"},qe=["value"],Fe={class:"sm:col-span-3"},Ke={class:"sm:col-span-3"},Te=e("div",{class:"sm:col-span-6"},[e("label",{for:"reserved",class:"italic text-blue-800"},' By setting "Reserved Percentage", the sellable qty equivalent to un-reserved percent, then that value if lower than "Reserved Quantity", channel becomes inactive, both default value are 0. ')],-1),Ee={class:"sm:col-span-6"},Le={class:"flex space-x-1 mt-5 justify-end"},Qe=e("span",null," Back ",-1),ze=e("span",null," Save ",-1),Ze=e("div",{class:"sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3"},[e("div",{class:"relative"},[e("div",{class:"absolute inset-0 flex items-center","aria-hidden":"true"},[e("div",{class:"w-full border-t border-gray-300"})]),e("div",{class:"relative flex justify-center"},[e("span",{class:"px-3 bg-white text-lg font-medium text-gray-900 rounded"},"Delivery Platform Product(s) ")])])],-1),Ge={key:0},He={key:1},Je=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Product ",-1),We={key:0,class:"text-sm text-red-600"},Xe={key:2},Ye={key:3},Ie=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Platform SubCategory ",-1),et={key:4,class:"sm:col-span-1"},tt=e("span",null," Add ",-1),st={key:5,class:"sm:col-span-6 flex flex-col mt-3"},ot={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5"},at={class:"inline-block min-w-full py-2 align-middle md:px-4 lg:px-6"},rt={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},lt={class:"table-fixed min-w-full divide-y divide-gray-300"},nt=e("thead",{class:"bg-gray-50"},[e("tr",null,[e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," # "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Channel ID "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Thumbnail "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Product "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Status "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Price "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Platform SubCategory "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Action ")])],-1),it={class:"bg-white"},dt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},ct={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},pt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},ut={class:"flex justify-center"},mt=["src"],_t={class:"whitespace-normal py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left flex flex-col"},vt={key:0},xt={key:1,class:"break-words"},gt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},ft={key:0,class:"inline-flex items-center rounded-md bg-green-300 px-1.5 py-0.5 text-xs font-medium text-green-800 ring-1 ring-inset ring-indigo-700/10"},yt={key:1,class:"inline-flex items-center rounded-md bg-red-200 px-1.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-indigo-700/10"},ht={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},bt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},wt={class:"whitespace-nowrap py-4 text-sm text-center flex flex-col space-y-1 px-2"},kt={key:2,class:"text-xs"},St={key:3,class:"text-xs"},Ct=e("span",{class:"text-xs"}," Unbind SKU ",-1),Vt={key:0},Ot=e("td",{colspan:"7",class:"whitespace-nowrap py-4 text-sm font-medium text-gray-600 text-center"}," No Records Found ",-1),Pt=[Ot],jt={key:6,class:"sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3"},At=e("div",{class:"relative"},[e("div",{class:"absolute inset-0 flex items-center","aria-hidden":"true"},[e("div",{class:"w-full border-t border-gray-300"})]),e("div",{class:"relative flex justify-center"},[e("span",{class:"px-3 bg-white text-lg font-medium text-gray-900 rounded"}," Vending Machine Binding ")])],-1),Mt=[At],$t={class:"sm:col-span-6"},Dt={class:"flex space-x-1 mt-5 justify-start"},Ut={key:2},Bt={key:3},Nt={key:7,class:"sm:col-span-5"},Rt=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Vending Machine ",-1),qt={key:0,class:"text-sm text-red-600"},Ft={key:8,class:"sm:col-span-1"},Kt=e("span",null," Add ",-1),Tt={key:9,class:"sm:col-span-6 flex flex-col mt-3"},Et={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5"},Lt={class:"inline-block min-w-full py-2 align-middle md:px-4 lg:px-6"},Qt={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},zt={class:"min-w-full divide-y divide-gray-300"},Zt=e("thead",{class:"bg-gray-50"},[e("tr",null,[e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," # "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Vend ID "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Vend Name "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Channel Status "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," VM Status "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Action ")])],-1),Gt={class:"bg-white"},Ht={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},Jt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},Wt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left"},Xt={key:0},Yt=e("br",null,null,-1),It={key:1},es={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},ts=["onClick"],ss={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},os={key:0,class:"inline-flex items-center rounded-md bg-green-300 px-1.5 py-0.5 text-xs font-medium text-green-800 ring-1 ring-inset ring-indigo-700/10"},as={key:1,class:"inline-flex items-center rounded-md bg-red-200 px-1.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-indigo-700/10"},rs={class:"whitespace-nowrap py-2 text-xs text-center p-3"},ls={class:"flex flex-col space-y-1"},ns={key:2,class:"text-xs"},is={key:3,class:"text-xs"},ds=e("span",{class:"text-xs"}," Unbind VM ",-1),cs={key:0},ps=e("td",{colspan:"6",class:"whitespace-nowrap py-4 text-sm font-medium text-gray-600 text-center"}," No Records Found ",-1),us=[ps],Ms={__name:"Edit",props:{categoryApiOptions:[Array,Object],deliveryProductMapping:Object,operatorOptions:Object,productMappingItems:Object,productMappingOptions:[Array,Object],productOptions:Object,type:String,unbindedVendOptions:[Array,Object]},setup(R){const n=R,z=m([]),O=m([]),Z=m([]),G=m([]),t=m($(T()));m(!1);const k=E().props.auth.operatorCountry,P=m([]),j=m([]),H=m([]),q=m([]),S=m(!1),F=m([]),A=m(""),J=E().props.auth.permissions,K=m(),W=m([]),M=m([]);ne(()=>{n.type=="create"?A.value="Create New":A.value="Edit",z.value=n.categoryApiOptions[0].categories.map(o=>({id:o.id,name:o.name,subCategories:o.subCategories})),O.value=[...n.operatorOptions.data.find(o=>o.id===n.deliveryProductMapping.data.operator_id).deliveryPlatformOperators.map(o=>({id:o.id,name:o.deliveryPlatform.name}))],G.value=n.deliveryProductMapping?n.deliveryProductMapping.data.deliveryProductMappingItems:[],P.value=[...n.operatorOptions.data.map(o=>({id:o.id,full_name:o.full_name}))],j.value=[...n.productMappingOptions[0].data.map(o=>({id:o.id,name:o.name}))],q.value=n.productOptions.data,t.value=n.deliveryProductMapping?$({...n.deliveryProductMapping.data,operator_id:P.value.find(o=>o.id===n.deliveryProductMapping.data.operator_id),operator_field:P.value.find(o=>o.id===n.deliveryProductMapping.data.operator_id).full_name,product_mapping_id:j.value.find(o=>o.id===n.deliveryProductMapping.data.product_mapping_id),product_mapping_field:j.value.find(o=>o.id===n.deliveryProductMapping.data.product_mapping_id).name,delivery_platform_operator_id:O.value.find(o=>o.id===n.deliveryProductMapping.data.delivery_platform_operator_id),delivery_platform_operator_field:O.value.find(o=>o.id===n.deliveryProductMapping.data.delivery_platform_operator_id).name}):$(T()),F.value=n.deliveryProductMapping?n.deliveryProductMapping.data.category_json.subCategories:[],M.value=[...n.unbindedVendOptions.data.map(o=>({id:o.id,full_name:o.full_name}))],W.value=n.deliveryProductMapping?n.deliveryProductMapping.data.vends:[]});function T(){return{id:"",category_json:"",delivery_platform_operator_id:"",name:"",operator_id:"",product_mapping_id:"",reserved_percent:0,reserved_qty:0,sub_category_json:""}}function X(){H.value.map(function(o){return o.channel_code}).indexOf(t.value.channel_code)<0&&h.post("/delivery-product-mapping-items/delivery-product-mapping/"+t.value.id+"/store",{...t.value,product_id:t.value.product_id?t.value.product_id.id:null},{preserveState:!1,preserveScroll:!0,replace:!0})}function Y(o){h.post("/delivery-product-mappings/"+t.value.id+"/bind-vend/"+o,{},{preserveState:!1,preserveScroll:!0,replace:!0})}function I(o){K.value=o,S.value=!0}function ee(){S.value=!1}function te(){t.value.clearErrors(),t.value.transform(o=>({name:o.name,reserved_percent:o.reserved_percent,reserved_qty:o.reserved_qty})).post("/delivery-product-mappings/"+t.value.id+"/update",{preserveState:!0,replace:!0})}function se(o){let l=t.value.is_active?"Are you sure to pause all vending machines?":"Are you sure to resume all vending machines?";!confirm(l)||h.post("/delivery-product-mappings/"+o+"/toggle-pause-all-vends",{preserveState:!1,preserveScroll:!0,replace:!0})}function oe(o){let l=o.is_active?"Are you sure to pause this SKU?":"Are you sure to resume this SKU?";!confirm(l)||h.post("/delivery-product-mapping-items/"+o.id+"/toggle-pause",{preserveState:!1,preserveScroll:!0,replace:!0})}function ae(o){let l=o.is_active?"Are you sure to pause this vending machine?":"Are you sure to resume this vending machine?";!confirm(l)||h.post("/delivery-product-mappings/vends/"+o.id+"/toggle-pause-vend",{preserveState:!1,preserveScroll:!0,replace:!0})}function re(o){!confirm("Are you sure to delete this entry?")||h.delete("/delivery-product-mapping-items/"+o,{preserveState:!1,preserveScroll:!0,replace:!0})}function le(o){!confirm("Are you sure to delete this entry?")||h.delete("/delivery-product-mappings/unbind/"+o,{preserveState:!1,preserveScroll:!0,replace:!0,onSuccess:()=>{h.reload({only:["unbindedVendOptions"]}),M.value=n.unbindedVendOptions?n.unbindedVendOptions.data:[]}})}return(o,l)=>(a(),r(V,null,[i(c(ie),{title:"Delivery Product Mapping"}),i(ce,null,{header:p(()=>[e("h2",_e,[g(u(A.value)+" Delivery Product Mapping ",1),R.type=="update"?(a(),r("span",ve,u(Z.value.name),1)):d("",!0)])]),default:p(()=>[e("div",xe,[e("div",ge,[e("div",fe,[e("div",ye,[e("form",{onSubmit:f(te,["prevent"]),id:"submit"},[e("div",be,[e("div",we,[i(w,{modelValue:t.value.name,"onUpdate:modelValue":l[0]||(l[0]=s=>t.value.name=s),error:t.value.errors.name},{default:p(()=>[g(" Name ")]),_:1},8,["modelValue","error"])]),e("div",ke,[Se,e("div",Ce,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed",value:t.value.operator_field},null,8,Ve)])]),e("div",Oe,[Pe,e("div",je,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed",value:t.value.delivery_platform_operator_field},null,8,Ae)])]),e("div",Me,[$e,e("div",De,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed",value:t.value.category_json.name},null,8,Ue)])]),e("div",Be,[Ne,e("div",Re,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed",value:t.value.product_mapping_field},null,8,qe)])]),e("div",Fe,[i(w,{modelValue:t.value.reserved_percent,"onUpdate:modelValue":l[1]||(l[1]=s=>t.value.reserved_percent=s),error:t.value.errors.reserved_percent},{default:p(()=>[g(" Reserved Percentage (%) ")]),_:1},8,["modelValue","error"])]),e("div",Ke,[i(w,{modelValue:t.value.reserved_qty,"onUpdate:modelValue":l[2]||(l[2]=s=>t.value.reserved_qty=s),error:t.value.errors.reserved_qty},{default:p(()=>[g(" Reserved Quantity ")]),_:1},8,["modelValue","error"])]),Te,e("div",Ee,[e("div",Le,[i(c(de),{href:"/delivery-product-mappings"},{default:p(()=>[i(x,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"},{default:p(()=>[i(c(ue),{class:"w-4 h-4"}),Qe]),_:1})]),_:1}),c(J).includes("update vends")?(a(),y(x,{key:0,type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:p(()=>[i(c(me),{class:"w-4 h-4"}),ze]),_:1})):d("",!0)])]),Ze,t.value.id?(a(),r("div",Ge,[i(w,{modelValue:t.value.channel_code,"onUpdate:modelValue":l[3]||(l[3]=s=>t.value.channel_code=s),error:t.value.errors.channel_code,placeholderStr:"Channel ID"},{default:p(()=>[g(" Channel ID ")]),_:1},8,["modelValue","error"])])):d("",!0),t.value.id?(a(),r("div",He,[Je,i(B,{modelValue:t.value.product_id,"onUpdate:modelValue":l[4]||(l[4]=s=>t.value.product_id=s),options:q.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),t.value.errors.product_id?(a(),r("div",We,u(t.value.errors.product_id),1)):d("",!0)])):d("",!0),t.value.id?(a(),r("div",Xe,[i(w,{modelValue:t.value.amount,"onUpdate:modelValue":l[5]||(l[5]=s=>t.value.amount=s),error:t.value.errors.amount,placeholderStr:"Platform Price"},{default:p(()=>[g(" Price ")]),_:1},8,["modelValue","error"])])):d("",!0),t.value.id?(a(),r("div",Ye,[Ie,i(B,{modelValue:t.value.sub_category_json,"onUpdate:modelValue":l[6]||(l[6]=s=>t.value.sub_category_json=s),options:F.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):d("",!0),t.value.product_mapping_id?(a(),r("div",et,[i(x,{type:"button",onClick:l[7]||(l[7]=f(s=>X(),["prevent"])),class:v(["bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6",[!t.value.channel_code||!t.value.product_id?"opacity-50 cursor-not-allowed":""]]),disabled:!t.value.channel_code||!t.value.product_id},{default:p(()=>[i(c(L),{class:"w-4 h-4"}),tt]),_:1},8,["class","disabled"])])):d("",!0),t.value.product_mapping_id?(a(),r("div",st,[e("div",ot,[e("div",at,[e("div",rt,[e("table",lt,[nt,e("tbody",it,[(a(!0),r(V,null,D(n.deliveryProductMapping.data.deliveryProductMappingItems,(s,b)=>(a(),r("tr",{key:s.id,class:v(b%2===0?void 0:"bg-gray-50")},[e("td",dt,u(b+1),1),e("td",ct,u(s.channel_code),1),e("td",pt,[e("div",ut,[s.product&&s.product.thumbnail?(a(),r("img",{key:0,class:"h-24 w-24 md:h-20 md:w-20 rounded-full",src:s.product.thumbnail.full_url,alt:""},null,8,mt)):d("",!0)])]),e("td",_t,[s.product.code?(a(),r("span",vt,u(s.product.code),1)):d("",!0),s.product.name?(a(),r("span",xt,u(s.product.name),1)):d("",!0)]),e("td",gt,[s.is_active==1?(a(),r("span",ft," Active ")):d("",!0),s.is_active==0?(a(),r("span",yt," Paused ")):d("",!0)]),e("td",ht,u(s.amount.toLocaleString(void 0,{minimumFractionDigits:c(k).is_currency_exponent_hidden?0:c(k).currency_exponent,maximumFractionDigits:c(k).is_currency_exponent_hidden?0:c(k).currency_exponent})),1),e("td",bt,u(s.sub_category_json.name),1),e("td",wt,[i(x,{class:v(["flex space-x-1",[s.is_active?"bg-yellow-300 hover:bg-yellow-400 text-black":"bg-green-500 hover:bg-green-600 text-white"]]),onClick:f(_=>oe(s),["prevent"])},{default:p(()=>[s.is_active?(a(),y(c(N),{key:0,class:"w-3 h-3"})):(a(),y(c(U),{key:1,class:"w-3 h-3"})),s.is_active?(a(),r("span",kt," Pause SKU ")):(a(),r("span",St," Resume SKU "))]),_:2},1032,["class","onClick"]),i(x,{class:"bg-red-400 hover:bg-red-500 text-white flex space-x-1",onClick:f(_=>re(s.id),["prevent"])},{default:p(()=>[i(c(Q),{class:"w-3 h-3"}),Ct]),_:2},1032,["onClick"])])],2))),128)),!n.deliveryProductMapping.data.deliveryProductMappingItems||!n.deliveryProductMapping.data.deliveryProductMappingItems.length?(a(),r("tr",Vt,Pt)):d("",!0)])])])])])])):d("",!0),t.value.product_mapping_id?(a(),r("div",jt,Mt)):d("",!0),e("div",$t,[e("div",Dt,[i(x,{class:v(["flex space-x-1",[t.value.is_active?"bg-yellow-300 hover:bg-yellow-400 text-black-700":"bg-green-500 hover:bg-green-600 text-white"]]),onClick:l[8]||(l[8]=f(s=>se(t.value.id),["prevent"]))},{default:p(()=>[t.value.is_active?(a(),y(c(N),{key:0,class:"w-4 h-4"})):(a(),y(c(U),{key:1,class:"w-4 h-4"})),t.value.is_active?(a(),r("span",Ut," Pause All Vending Machines ")):(a(),r("span",Bt," Resume All Vending Machines "))]),_:1},8,["class"])])]),t.value.product_mapping_id?(a(),r("div",Nt,[Rt,i(B,{modelValue:t.value.vend_id,"onUpdate:modelValue":l[9]||(l[9]=s=>t.value.vend_id=s),options:M.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),t.value.errors.vend_id?(a(),r("div",qt,u(t.value.errors.vend_id),1)):d("",!0)])):d("",!0),t.value.product_mapping_id?(a(),r("div",Ft,[i(x,{type:"button",onClick:l[10]||(l[10]=f(s=>Y(t.value.vend_id.id),["prevent"])),class:v(["bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6",[t.value.vend_id?"":"opacity-50 cursor-not-allowed"]]),disabled:!t.value.vend_id},{default:p(()=>[i(c(L),{class:"w-4 h-4"}),Kt]),_:1},8,["class","disabled"])])):d("",!0),t.value.product_mapping_id?(a(),r("div",Tt,[e("div",Et,[e("div",Lt,[e("div",Qt,[e("table",zt,[Zt,e("tbody",Gt,[(a(!0),r(V,null,D(n.deliveryProductMapping.data.deliveryProductMappingVends,(s,b)=>(a(),r("tr",{key:s.id,class:v(b%2===0?void 0:"bg-gray-50")},[e("td",Ht,u(b+1),1),e("td",Jt,u(s.vend.code),1),e("td",Wt,[s.vend.latestVendBinding&&s.vend.latestVendBinding.customer?(a(),r("span",Xt,[g(u(s.vend.latestVendBinding.customer.code)+" ",1),Yt,g(" "+u(s.vend.latestVendBinding.customer.name),1)])):(a(),r("span",It,u(s.vend.name),1))]),e("td",es,[s.deliveryProductMappingVendChannels?(a(),r("ul",{key:0,class:"sm:grid sm:grid-cols-[105px_minmax(110px,_1fr)_100px] hover:cursor-pointer",onClick:_=>I(s)},[(a(!0),r(V,null,D(s.deliveryProductMappingVendChannels,(_,C)=>(a(),r("li",{class:v(["quick-look",[C>0&&String(_.vend_channel_code)[0]!==String(s.deliveryProductMappingVendChannels[C-1].vend_channel_code)[0]?"col-start-1":""]])},[e("span",{class:v([[C>0&&String(_.vend_channel_code)[0]!==String(s.deliveryProductMappingVendChannels[C-1].vend_channel_code)[0]?"border-t-4 pt-1":""],"flex space-x-1"])},[e("span",null," #"+u(_.vend_channel_code)+", ",1),e("span",null,u(_.delivery_product_mapping_item.product?_.delivery_product_mapping_item.product.code:""),1),e("span",{class:v(["inline-flex items-center rounded-md px-1.5 text-xs font-medium text-green-800 ring-1 ring-inset ring-indigo-700/10",[_.is_active==1?"bg-green-500":"bg-red-500"]])},null,2)],2)],2))),256))],8,ts)):d("",!0)]),e("td",ss,[s.is_active==1?(a(),r("span",os," Operating ")):d("",!0),s.is_active==0?(a(),r("span",as," Paused ")):d("",!0)]),e("td",rs,[e("div",ls,[i(x,{class:v(["flex space-x-1",[s.is_active?"bg-yellow-300 hover:bg-yellow-400 text-black":"bg-green-500 hover:bg-green-600 text-white"]]),onClick:f(_=>ae(s),["prevent"])},{default:p(()=>[s.is_active?(a(),y(c(N),{key:0,class:"w-3 h-3"})):(a(),y(c(U),{key:1,class:"w-3 h-3"})),s.is_active?(a(),r("span",ns," Pause VM ")):(a(),r("span",is," Resume VM "))]),_:2},1032,["class","onClick"]),i(x,{class:"bg-red-400 hover:bg-red-500 text-white flex space-x-1",onClick:f(_=>le(s.id),["prevent"])},{default:p(()=>[i(c(Q),{class:"w-3 h-3"}),ds]),_:2},1032,["onClick"])])])],2))),128)),n.deliveryProductMapping.data.deliveryProductMappingVends.length?d("",!0):(a(),r("tr",cs,us))])])])])])])):d("",!0)])],40,he)])])])]),S.value?(a(),y(pe,{key:0,vend:K.value,showModal:S.value,onModalClose:ee},null,8,["vend","showModal"])):d("",!0)]),_:1})],64))}};export{Ms as default};
