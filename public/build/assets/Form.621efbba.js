import{g as _,T as O,h as L,c as P,a as r,w as n,p as Q,o as s,b as t,f as o,l as d,t as i,d as p,n as v,u as f,F as C,k as S,e as H,O as I}from"./app.009697ae.js";import{_ as x}from"./Button.37f68050.js";import{_ as y}from"./FormInput.262e39f7.js";import{_ as W}from"./FormTextarea.7806cfec.js";import{_ as X}from"./Modal.6b7b8f6f.js";import{_ as w}from"./MultiSelect.418dfc00.js";import{r as k}from"./PlusCircleIcon.4be410ed.js";import{r as Y}from"./ArrowUturnLeftIcon.274a55d8.js";import{r as Z}from"./CheckCircleIcon.dcf41f14.js";import{r as j}from"./BackspaceIcon.65987218.js";import"./open-closed.0a17ce87.js";import"./disposables.a63b89fa.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.dbddf1f4.js";const ee={class:"flex flex-col md:flex-row space-x-2"},te={key:0,class:"text-gray-600"},ae={key:1},le={key:2,class:"text-gray-600"},se=["onSubmit"],oe={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},re={class:"sm:col-span-2"},de={class:"sm:col-span-4"},ie={class:"sm:col-span-3"},ne=t("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Country ",-1),me={key:0,class:"text-sm text-red-600"},ue={class:"sm:col-span-3"},pe=t("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Timezone ",-1),ce={key:0,class:"text-sm text-red-600"},ye={class:"sm:col-span-4"},_e=t("span",{class:"text-[9px]"}," (For Gross Margin Calculation) ",-1),ve={class:"sm:col-span-6"},fe={key:0,class:"sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3"},xe=t("div",{class:"relative"},[t("div",{class:"absolute inset-0 flex items-center","aria-hidden":"true"},[t("div",{class:"w-full border-t border-gray-300"})]),t("div",{class:"relative flex justify-center"},[t("span",{class:"px-3 bg-white text-lg font-medium text-gray-900"}," QR Payment Gateway(s) ")])],-1),ge=[xe],he=t("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Payment Gateway ",-1),we={key:0,class:"text-sm text-red-600"},be={key:2,class:"sm:col-span-1"},ke=t("span",null," Add ",-1),Ve={key:3,class:"sm:col-span-3"},Oe={key:4,class:"sm:col-span-3"},Pe={key:5,class:"sm:col-span-3"},Ce={key:6,class:"sm:col-span-3"},Se=t("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"},[p(" Type "),t("span",{class:"text-red-500"},"*")],-1),je={key:0,class:"text-sm text-red-600"},Ae={key:7,class:"sm:col-span-6 flex justify-end"},Ue=t("span",null," Add ",-1),$e={key:8,class:"sm:col-span-6 flex flex-col mt-3"},Be={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5"},Ne={class:"inline-block min-w-full py-2 align-middle md:px-4 lg:px-6"},Te={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},ze={class:"min-w-full divide-y divide-gray-300"},Ge=t("thead",{class:"bg-gray-50"},[t("tr",null,[t("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," # "),t("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Name "),t("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Type "),t("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Private Key "),t("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Action ")])],-1),De={class:"bg-white"},Me={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},Fe={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},Re={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},Je={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},qe={class:"whitespace-nowrap py-4 text-sm text-center"},Ee={key:0},Ke=t("td",{colspan:"5",class:"whitespace-nowrap py-4 text-sm font-medium text-center"}," No Result Found ",-1),Le=[Ke],Qe={key:9,class:"sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3"},He=t("div",{class:"relative"},[t("div",{class:"absolute inset-0 flex items-center","aria-hidden":"true"},[t("div",{class:"w-full border-t border-gray-300"})]),t("div",{class:"relative flex justify-center"},[t("span",{class:"px-3 bg-white text-lg font-medium text-gray-900"}," Delivery Platform(s) ")])],-1),Ie=[He],We=t("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Delivery Platform ",-1),Xe={key:0,class:"text-sm text-red-600"},Ye={key:11,class:"sm:col-span-1"},Ze=t("span",null," Add ",-1),et={key:12,class:"sm:col-span-3"},tt={key:13,class:"sm:col-span-3"},at={key:14,class:"sm:col-span-3"},lt={key:15,class:"sm:col-span-3"},st={key:16,class:"sm:col-span-3"},ot={key:17,class:"sm:col-span-3"},rt={key:18,class:"sm:col-span-3"},dt=t("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"},[p(" Type "),t("span",{class:"text-red-500"},"*")],-1),it={key:0,class:"text-sm text-red-600"},nt={key:19,class:"sm:col-span-6 flex justify-end"},mt=t("span",null," Add ",-1),ut={key:20,class:"sm:col-span-6 flex flex-col mt-3"},pt={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5"},ct={class:"inline-block min-w-full py-2 align-middle md:px-4 lg:px-6"},yt={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},_t={class:"min-w-full divide-y divide-gray-300"},vt=t("thead",{class:"bg-gray-50"},[t("tr",null,[t("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," # "),t("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Name "),t("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Type "),t("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Merchant ID "),t("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Action ")])],-1),ft={class:"bg-white"},xt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},gt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},ht={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},wt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},bt={class:"whitespace-nowrap py-4 text-sm text-center"},kt={key:0},Vt=t("td",{colspan:"5",class:"whitespace-nowrap py-4 text-sm font-medium text-center"}," No Result Found ",-1),Ot=[Vt],Pt={key:21,class:"sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3"},Ct=t("div",{class:"relative"},[t("div",{class:"absolute inset-0 flex items-center","aria-hidden":"true"},[t("div",{class:"w-full border-t border-gray-300"})]),t("div",{class:"relative flex justify-center"},[t("span",{class:"px-3 bg-white text-lg font-medium text-gray-900"}," Access Vending Machine(s) ")])],-1),St=[Ct],jt={key:22,class:"sm:col-span-5"},At=t("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Vending Machine to Bind ",-1),Ut={key:0,class:"text-sm text-red-600"},$t={key:23,class:"sm:col-span-1"},Bt=t("span",null," Add ",-1),Nt={key:24,class:"sm:col-span-6 flex flex-col mt-3"},Tt={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5"},zt={class:"inline-block min-w-full py-2 align-middle md:px-4 lg:px-6"},Gt={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},Dt={class:"min-w-full divide-y divide-gray-300"},Mt=t("thead",{class:"bg-gray-50"},[t("tr",null,[t("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," # "),t("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Vend ID "),t("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Name "),t("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Action ")])],-1),Ft={class:"bg-white"},Rt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},Jt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},qt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},Et={key:0},Kt=t("br",null,null,-1),Lt={key:1},Qt={class:"whitespace-nowrap py-4 text-sm text-center"},Ht={key:0},It=t("td",{colspan:"4",class:"whitespace-nowrap py-4 text-sm font-medium text-red-600 text-center"}," No Binding = Access to All ",-1),Wt=[It],Xt={class:"sm:col-span-6"},Yt={class:"flex space-x-1 mt-5 justify-end"},Zt=t("span",null," Back ",-1),ea=t("span",null," Save ",-1),ya={__name:"Form",props:{countries:[Array,Object],operator:Object,timezones:[Array,Object],type:String,showModal:Boolean,countryDeliveryPlatforms:[Array,Object],countryPaymentGateways:[Array,Object],deliveryPlatformOperatorTypes:[Array,Object],operatorPaymentGatewayTypes:[Array,Object],unbindedVends:[Array,Object],permissions:[Array,Object]},emits:["modalClose"],setup(c,{emit:V}){const m=c,A=_([]),e=_(O(G())),U=_([]),$=_([]),B=_([]),N=_([]),T=_([]),b=_([]),z=_([]),h=_([]);L(()=>{$.value=m.countryDeliveryPlatforms.data,B.value=m.countryPaymentGateways.data,A.value=m.countries.data,N.value=m.operator?m.operator.deliveryPlatformOperators:null,T.value=m.deliveryPlatformOperatorTypes,U.value=m.timezones.map((u,l)=>({id:l,name:u})),z.value=m.operatorPaymentGatewayTypes,h.value=m.unbindedVends.data,b.value=m.operator?m.operator.operatorPaymentGateways:null,e.value=m.operator?O(m.operator):O(G())});function G(){return{id:"",code:"",name:"",gst_vat_rate:"",country_id:"",delivery_platform_id:"",delivery_platform_type:"",delivery_platform_field1:"",delivery_platform_field2:"",delivery_platform_field3:"",delivery_platform_field4:"",delivery_platform_oauth_client_id:"",delivery_platform_oauth_client_secret:"",payment_gateway_id:"",payment_gateway_type:"",payment_gateway_key1:"",payment_gateway_key2:"",payment_gateway_key3:"",timezone:"",remarks:"",vend_id:""}}function R(){m.operator.vends.indexOf(e.value.vend_id)<0&&(m.operator.vends.push(e.value.vend_id),m.operator.vends.sort((u,l)=>u.code-l.code),h.value.splice(h.value.indexOf(e.value.vend_id),1),h.value.sort((u,l)=>u.code-l.code))}function J(u){m.operator.vends.splice(m.operator.vends.indexOf(u),1),h.value.push(u),h.value.sort((l,a)=>l.code-a.code)}function D(){e.value.transform(u=>({delivery_platform_id:e.value.delivery_platform.id,field1:e.value.delivery_platform_field1,field2:e.value.delivery_platform_field2,field3:e.value.delivery_platform_field3,field4:e.value.delivery_platform_field4,oauth_client_id:e.value.delivery_platform_oauth_client_id,oauth_client_secret:e.value.delivery_platform_oauth_client_secret,type:e.value.delivery_platform_type.id,deliveryPlatform:JSON.parse(JSON.stringify(e.value.delivery_platform))})).post("/operators/"+e.value.id+"/delivery-platform/create",{onSuccess:()=>{V("modalClose")},preserveState:!0,replace:!0})}function M(){b.value.indexOf(e.value.payment_gateway)<0&&b.value.push({id:e.value.payment_gateway_id,key1:e.value.payment_gateway_key1,key2:e.value.payment_gateway_key2,key3:e.value.payment_gateway_key3,type:e.value.payment_gateway_type.id,paymentGateway:JSON.parse(JSON.stringify(e.value.payment_gateway))})}function q(u){!confirm("Are you sure to delete this entry?")||I.delete("/operators/delivery-platform/"+u.id)}function E(u){b.value.splice(b.value.indexOf(u),1)}function K(){e.value.clearErrors(),m.type==="create"&&e.value.transform(u=>({...u,timezone:u.timezone.name,country_id:u.country_id.id})).post("/operators/create",{onSuccess:()=>{V("modalClose")},preserveState:!0,replace:!0}),m.type==="update"&&e.value.transform(u=>({...u,timezone:u.timezone.name,country_id:u.country_id.id,deliveryPlatforms:N.value,operator:m.operator,paymentGateways:b.value})).post("/operators/"+e.value.id+"/update",{onSuccess:()=>{V("modalClose")},preserveState:!0,replace:!0})}return(u,l)=>(s(),P(Q,{to:"body"},[r(X,{open:c.showModal,onModalClose:l[26]||(l[26]=a=>u.$emit("modalClose"))},{header:n(()=>[t("div",ee,[m.operator?(s(),o("span",te," Editing ")):d("",!0),m.operator?(s(),o("span",ae,i(m.operator.name),1)):(s(),o("span",le," Create New Operator "))])]),default:n(()=>[t("form",{onSubmit:H(K,["prevent"]),id:"submit"},[t("div",oe,[t("div",re,[r(y,{modelValue:e.value.code,"onUpdate:modelValue":l[0]||(l[0]=a=>e.value.code=a),error:e.value.errors.code},{default:n(()=>[p(" Code ")]),_:1},8,["modelValue","error"])]),t("div",de,[r(y,{modelValue:e.value.name,"onUpdate:modelValue":l[1]||(l[1]=a=>e.value.name=a),error:e.value.errors.name,required:"true"},{default:n(()=>[p(" Name ")]),_:1},8,["modelValue","error"])]),t("div",ie,[ne,r(w,{modelValue:e.value.country_id,"onUpdate:modelValue":l[2]||(l[2]=a=>e.value.country_id=a),options:A.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),e.value.errors.country_id?(s(),o("div",me,i(e.value.errors.country_id),1)):d("",!0)]),t("div",ue,[pe,r(w,{modelValue:e.value.timezone,"onUpdate:modelValue":l[3]||(l[3]=a=>e.value.timezone=a),options:U.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),e.value.errors.timezone?(s(),o("div",ce,i(e.value.errors.timezone),1)):d("",!0)]),t("div",ye,[r(y,{modelValue:e.value.gst_vat_rate,"onUpdate:modelValue":l[4]||(l[4]=a=>e.value.gst_vat_rate=a),error:e.value.errors.gst_vat_rate},{default:n(()=>[p(" GST or VAT Rate (%) "),_e]),_:1},8,["modelValue","error"])]),t("div",ve,[r(W,{modelValue:e.value.remarks,"onUpdate:modelValue":l[5]||(l[5]=a=>e.value.remarks=a),error:e.value.errors.remarks},{default:n(()=>[p(" Remarks ")]),_:1},8,["modelValue","error"])]),e.value.id?(s(),o("div",fe,ge)):d("",!0),e.value.id?(s(),o("div",{key:1,class:v([e.value.payment_gateway?"sm:col-span-6":"sm:col-span-5"])},[he,r(w,{modelValue:e.value.payment_gateway,"onUpdate:modelValue":l[6]||(l[6]=a=>e.value.payment_gateway=a),options:B.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1",ref:"multiselect"},null,8,["modelValue","options"]),e.value.errors.payment_gateway?(s(),o("div",we,i(e.value.errors.payment_gateway),1)):d("",!0)],2)):d("",!0),e.value.id&&!e.value.payment_gateway?(s(),o("div",be,[r(x,{type:"button",onClick:l[7]||(l[7]=a=>M()),class:v(["bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6",[!e.value.payment_gateway||!e.value.payment_gateway_type?"opacity-50 cursor-not-allowed":""]]),disabled:!e.value.payment_gateway||!e.value.payment_gateway_type},{default:n(()=>[r(f(k),{class:"w-4 h-4"}),ke]),_:1},8,["class","disabled"])])):d("",!0),e.value.id&&e.value.payment_gateway&&e.value.payment_gateway.key1_name?(s(),o("div",Ve,[r(y,{modelValue:e.value.payment_gateway_key1,"onUpdate:modelValue":l[8]||(l[8]=a=>e.value.payment_gateway_key1=a),error:e.value.errors.payment_gateway_key1,required:"true"},{default:n(()=>[p(i(e.value.payment_gateway.key1_name),1)]),_:1},8,["modelValue","error"])])):d("",!0),e.value.id&&e.value.payment_gateway&&e.value.payment_gateway.key2_name?(s(),o("div",Oe,[r(y,{modelValue:e.value.payment_gateway_key2,"onUpdate:modelValue":l[9]||(l[9]=a=>e.value.payment_gateway_key2=a),error:e.value.errors.payment_gateway_key2},{default:n(()=>[p(i(e.value.payment_gateway.key2_name),1)]),_:1},8,["modelValue","error"])])):d("",!0),e.value.id&&e.value.payment_gateway&&e.value.payment_gateway.key3_name?(s(),o("div",Pe,[r(y,{modelValue:e.value.payment_gateway_key3,"onUpdate:modelValue":l[10]||(l[10]=a=>e.value.payment_gateway_key3=a),error:e.value.errors.payment_gateway_key3},{default:n(()=>[p(i(e.value.payment_gateway.key3_name),1)]),_:1},8,["modelValue","error"])])):d("",!0),e.value.id&&e.value.payment_gateway?(s(),o("div",Ce,[Se,r(w,{modelValue:e.value.payment_gateway_type,"onUpdate:modelValue":l[11]||(l[11]=a=>e.value.payment_gateway_type=a),options:z.value,trackBy:"id",valueProp:"id",label:"id",placeholder:"Select","open-direction":"bottom",class:"mt-1",ref:"multiselect"},null,8,["modelValue","options"]),e.value.errors.payment_gateway_type?(s(),o("div",je,i(e.value.errors.payment_gateway_type),1)):d("",!0)])):d("",!0),e.value.id&&e.value.payment_gateway?(s(),o("div",Ae,[r(x,{type:"button",onClick:l[12]||(l[12]=a=>M()),class:v(["bg-green-500 hover:bg-green-600 text-white",[!e.value.payment_gateway||!e.value.payment_gateway_type||!e.value.payment_gateway_key1?"opacity-50 cursor-not-allowed":""]]),disabled:!e.value.payment_gateway||!e.value.payment_gateway_type||!e.value.payment_gateway_key1||!c.permissions.includes("update operators")},{default:n(()=>[r(f(k),{class:"w-4 h-4"}),Ue]),_:1},8,["class","disabled"])])):d("",!0),e.value.id?(s(),o("div",$e,[t("div",Be,[t("div",Ne,[t("div",Te,[t("table",ze,[Ge,t("tbody",De,[(s(!0),o(C,null,S(c.operator.operatorPaymentGateways,(a,g)=>(s(),o("tr",{key:a.id,class:v(g%2===0?void 0:"bg-gray-50")},[t("td",Me,i(g+1),1),t("td",Fe,i(a.paymentGateway.name),1),t("td",Re,i(a.type),1),t("td",Je,i(a.key1),1),t("td",qe,[r(x,{class:"bg-red-400 hover:bg-red-500 text-white",onClick:F=>E(a)},{default:n(()=>[r(f(j),{class:"w-4 h-4"})]),_:2},1032,["onClick"])])],2))),128)),c.operator.operatorPaymentGateways.length?d("",!0):(s(),o("tr",Ee,Le))])])])])])])):d("",!0),e.value.id?(s(),o("div",Qe,Ie)):d("",!0),e.value.id?(s(),o("div",{key:10,class:v([e.value.payment_gateway?"sm:col-span-6":"sm:col-span-5"])},[We,r(w,{modelValue:e.value.delivery_platform,"onUpdate:modelValue":l[13]||(l[13]=a=>e.value.delivery_platform=a),options:$.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1",ref:"multiselect"},null,8,["modelValue","options"]),e.value.errors.delivery_platform?(s(),o("div",Xe,i(e.value.errors.delivery_platform),1)):d("",!0)],2)):d("",!0),e.value.id&&!e.value.delivery_platform?(s(),o("div",Ye,[r(x,{type:"button",onClick:l[14]||(l[14]=a=>D()),class:v(["bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6",[!e.value.delivery_platform||!e.value.delivery_platform_type?"opacity-50 cursor-not-allowed":""]]),disabled:!e.value.delivery_platform||!e.value.delivery_platform_type||!c.permissions.includes("update operators")},{default:n(()=>[r(f(k),{class:"w-4 h-4"}),Ze]),_:1},8,["class","disabled"])])):d("",!0),e.value.id&&e.value.delivery_platform&&e.value.delivery_platform.field1_name?(s(),o("div",et,[r(y,{modelValue:e.value.delivery_platform_field1,"onUpdate:modelValue":l[15]||(l[15]=a=>e.value.delivery_platform_field1=a),error:e.value.errors.delivery_platform_field1,required:"true"},{default:n(()=>[p(i(e.value.delivery_platform.field1_name),1)]),_:1},8,["modelValue","error"])])):d("",!0),e.value.id&&e.value.delivery_platform&&e.value.delivery_platform.field2_name?(s(),o("div",tt,[r(y,{modelValue:e.value.delivery_platform_field2,"onUpdate:modelValue":l[16]||(l[16]=a=>e.value.delivery_platform_field2=a),error:e.value.errors.delivery_platform_field2},{default:n(()=>[p(i(e.value.delivery_platform.field2_name),1)]),_:1},8,["modelValue","error"])])):d("",!0),e.value.id&&e.value.delivery_platform&&e.value.delivery_platform.field3_name?(s(),o("div",at,[r(y,{modelValue:e.value.delivery_platform_field3,"onUpdate:modelValue":l[17]||(l[17]=a=>e.value.delivery_platform_field3=a),error:e.value.errors.delivery_platform_field3},{default:n(()=>[p(i(e.value.delivery_platform.field3_name),1)]),_:1},8,["modelValue","error"])])):d("",!0),e.value.id&&e.value.delivery_platform&&e.value.delivery_platform.field4_name?(s(),o("div",lt,[r(y,{modelValue:e.value.delivery_platform_field4,"onUpdate:modelValue":l[18]||(l[18]=a=>e.value.delivery_platform_field4=a),error:e.value.errors.delivery_platform_field4},{default:n(()=>[p(i(e.value.delivery_platform.field4_name),1)]),_:1},8,["modelValue","error"])])):d("",!0),e.value.id&&e.value.delivery_platform&&e.value.delivery_platform.default_access_method=="oauth"?(s(),o("div",st,[r(y,{modelValue:e.value.delivery_platform_oauth_client_id,"onUpdate:modelValue":l[19]||(l[19]=a=>e.value.delivery_platform_oauth_client_id=a),error:e.value.errors.delivery_platform_oauth_client_id},{default:n(()=>[p(" Oauth Client ID ")]),_:1},8,["modelValue","error"])])):d("",!0),e.value.id&&e.value.delivery_platform&&e.value.delivery_platform.default_access_method=="oauth"?(s(),o("div",ot,[r(y,{modelValue:e.value.delivery_platform_oauth_client_secret,"onUpdate:modelValue":l[20]||(l[20]=a=>e.value.delivery_platform_oauth_client_secret=a),error:e.value.errors.delivery_platform_oauth_client_secret},{default:n(()=>[p(" Oauth Client Secret ")]),_:1},8,["modelValue","error"])])):d("",!0),e.value.id&&e.value.delivery_platform?(s(),o("div",rt,[dt,r(w,{modelValue:e.value.delivery_platform_type,"onUpdate:modelValue":l[21]||(l[21]=a=>e.value.delivery_platform_type=a),options:T.value,trackBy:"id",valueProp:"id",label:"id",placeholder:"Select","open-direction":"bottom",class:"mt-1",ref:"multiselect"},null,8,["modelValue","options"]),e.value.errors.delivery_platform_type?(s(),o("div",it,i(e.value.errors.delivery_platform_type),1)):d("",!0)])):d("",!0),e.value.id&&e.value.delivery_platform?(s(),o("div",nt,[r(x,{type:"button",onClick:l[22]||(l[22]=a=>D()),class:v(["bg-green-500 hover:bg-green-600 text-white",[!e.value.delivery_platform||!e.value.delivery_platform_type||!e.value.delivery_platform_field1?"opacity-50 cursor-not-allowed":""]]),disabled:!e.value.delivery_platform||!e.value.delivery_platform_type||!e.value.delivery_platform_field1||!c.permissions.includes("update operators")},{default:n(()=>[r(f(k),{class:"w-4 h-4"}),mt]),_:1},8,["class","disabled"])])):d("",!0),e.value.id?(s(),o("div",ut,[t("div",pt,[t("div",ct,[t("div",yt,[t("table",_t,[vt,t("tbody",ft,[(s(!0),o(C,null,S(c.operator.deliveryPlatformOperators,(a,g)=>(s(),o("tr",{key:a.id,class:v(g%2===0?void 0:"bg-gray-50")},[t("td",xt,i(g+1),1),t("td",gt,i(a.deliveryPlatform.name),1),t("td",ht,i(a.type),1),t("td",wt,i(a.field1),1),t("td",bt,[r(x,{class:"bg-red-400 hover:bg-red-500 text-white",onClick:F=>q(a)},{default:n(()=>[r(f(j),{class:"w-4 h-4"})]),_:2},1032,["onClick"])])],2))),128)),c.operator.deliveryPlatformOperators.length?d("",!0):(s(),o("tr",kt,Ot))])])])])])])):d("",!0),e.value.id?(s(),o("div",Pt,St)):d("",!0),e.value.id?(s(),o("div",jt,[At,r(w,{modelValue:e.value.vend_id,"onUpdate:modelValue":l[23]||(l[23]=a=>e.value.vend_id=a),options:h.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1",ref:"multiselect"},null,8,["modelValue","options"]),e.value.errors.vend_id?(s(),o("div",Ut,i(e.value.errors.vend_id),1)):d("",!0)])):d("",!0),e.value.id?(s(),o("div",$t,[r(x,{type:"button",onClick:l[24]||(l[24]=a=>R()),class:v(["bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6",[e.value.vend_id?"":"opacity-50 cursor-not-allowed"]]),disabled:!e.value.vend_id&&!c.permissions.includes("update operators")},{default:n(()=>[r(f(k),{class:"w-4 h-4"}),Bt]),_:1},8,["class","disabled"])])):d("",!0),e.value.id?(s(),o("div",Nt,[t("div",Tt,[t("div",zt,[t("div",Gt,[t("table",Dt,[Mt,t("tbody",Ft,[(s(!0),o(C,null,S(c.operator.vends,(a,g)=>(s(),o("tr",{key:a.id,class:v(g%2===0?void 0:"bg-gray-50")},[t("td",Rt,i(g+1),1),t("td",Jt,i(a.code),1),t("td",qt,[a.latestVendBinding&&a.latestVendBinding.customer?(s(),o("span",Et,[p(i(a.latestVendBinding.customer.code)+" ",1),Kt,p(" "+i(a.latestVendBinding.customer.name),1)])):(s(),o("span",Lt,i(a.name),1))]),t("td",Qt,[c.permissions.includes("update operators")?(s(),P(x,{key:0,class:"bg-red-400 hover:bg-red-500 text-white",onClick:F=>J(a)},{default:n(()=>[r(f(j),{class:"w-4 h-4"})]),_:2},1032,["onClick"])):d("",!0)])],2))),128)),c.operator.vends.length?d("",!0):(s(),o("tr",Ht,Wt))])])])])])])):d("",!0)]),t("div",Xt,[t("div",Yt,[r(x,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:l[25]||(l[25]=a=>u.$emit("modalClose")),form:"submit"},{default:n(()=>[r(f(Y),{class:"w-4 h-4"}),Zt]),_:1}),c.permissions.includes("update operators")?(s(),P(x,{key:0,type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:n(()=>[r(f(Z),{class:"w-4 h-4"}),ea]),_:1})):d("",!0)])])],40,se)]),_:1},8,["open"])]))}};export{ya as default};
